package com.amizo.glowchat;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.lifecycle.LiveData;
import androidx.lifecycle.Observer;
import androidx.lifecycle.ViewModelProvider;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.amizo.glowchat.data.local.entity.ConversationEntity;
import com.amizo.glowchat.ui.chats.ChatActivity;
import com.amizo.glowchat.ui.chats.ConversationAdapter;
import com.amizo.glowchat.ui.chats.ConversationViewModel;
import com.amizo.glowchat.ui.main.MainViewModel;
import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.google.android.material.chip.ChipGroup;

import java.util.List;

public class MainActivity extends AppCompatActivity {

    private MainViewModel mainViewModel;
    private ConversationViewModel conversationViewModel;

    private TextView emptyTitle;
    private TextView emptyMessage;

    private LinearLayout emptyChatsView;
    private RecyclerView conversationRecyclerView;
    private ChipGroup chatFilterGroup;
    private View newChatButton;

    private ConversationAdapter conversationAdapter;

    private LiveData<List<ConversationEntity>> activeConversationSource;

    private final Observer<List<ConversationEntity>> conversationObserver =
            conversations -> {
                if (conversations == null || conversations.isEmpty()) {
                    conversationAdapter.submitList(null);
                    conversationRecyclerView.setVisibility(View.GONE);
                    emptyChatsView.setVisibility(View.VISIBLE);
                } else {
                    conversationAdapter.submitList(conversations);
                    conversationRecyclerView.setVisibility(View.VISIBLE);
                    emptyChatsView.setVisibility(View.GONE);
                }
            };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WindowCompat.enableEdgeToEdge(getWindow());
        setContentView(R.layout.activity_main);

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main),
                (view, insets) -> {
                    Insets systemBars = insets.getInsets(
                            WindowInsetsCompat.Type.systemBars());
                    view.setPadding(
                            systemBars.left,
                            systemBars.top,
                            systemBars.right,
                            systemBars.bottom
                    );
                    return insets;
                });

        mainViewModel = new ViewModelProvider(this)
                .get(MainViewModel.class);

        conversationViewModel = new ViewModelProvider(this)
                .get(ConversationViewModel.class);

        emptyTitle = findViewById(R.id.text_empty_title);
        emptyMessage = findViewById(R.id.text_empty_message);
        emptyChatsView = findViewById(R.id.empty_chats_view);
        newChatButton = findViewById(R.id.button_new_chat);

        newChatButton.setOnClickListener(view -> {
            Intent intent = new Intent(
                    MainActivity.this,
                    com.amizo.glowchat.ui.chats.NewChatActivity.class
            );
            startActivity(intent);
        });

        conversationRecyclerView =
                findViewById(R.id.recycler_conversations);

        conversationRecyclerView.setLayoutManager(
                new LinearLayoutManager(this)
        );

        conversationAdapter = new ConversationAdapter(conversation -> {
            Intent intent = new Intent(this, ChatActivity.class);
            intent.putExtra(
                    ChatActivity.EXTRA_CONVERSATION_ID,
                    conversation.conversationId
            );
            startActivity(intent);
        });

        conversationRecyclerView.setAdapter(conversationAdapter);

        chatFilterGroup = findViewById(R.id.chat_filter_group);

        chatFilterGroup.setOnCheckedStateChangeListener((group, checkedIds) -> {
            if (checkedIds.isEmpty()) {
                return;
            }

            int checkedId = checkedIds.get(0);

            if (checkedId == R.id.filter_all) {
                mainViewModel.selectChatFilter("all");
            } else if (checkedId == R.id.filter_unread) {
                mainViewModel.selectChatFilter("unread");
            } else if (checkedId == R.id.filter_favorites) {
                mainViewModel.selectChatFilter("favorites");
            } else if (checkedId == R.id.filter_groups) {
                mainViewModel.selectChatFilter("groups");
            } else if (checkedId == R.id.filter_archived) {
                mainViewModel.selectChatFilter("archived");
            }
        });

        mainViewModel.getSelectedChatFilter().observe(this, filter -> {
            if (filter == null) {
                return;
            }

            int chipId;

            switch (filter) {
                case "unread":
                    chipId = R.id.filter_unread;
                    break;
                case "favorites":
                    chipId = R.id.filter_favorites;
                    break;
                case "groups":
                    chipId = R.id.filter_groups;
                    break;
                case "archived":
                    chipId = R.id.filter_archived;
                    break;
                case "all":
                default:
                    chipId = R.id.filter_all;
                    break;
            }

            if (chatFilterGroup.getCheckedChipId() != chipId) {
                chatFilterGroup.check(chipId);
            }

            if ("chats".equals(mainViewModel.getSelectedDestination().getValue())) {
                observeConversations(filter);
            }
        });

        BottomNavigationView bottomNavigation =
                findViewById(R.id.bottom_navigation);

        bottomNavigation.setOnItemSelectedListener(item -> {
            int itemId = item.getItemId();

            if (itemId == R.id.navigation_chats) {
                mainViewModel.selectDestination("chats");
            } else if (itemId == R.id.navigation_contacts) {
                mainViewModel.selectDestination("contacts");
            } else if (itemId == R.id.navigation_status) {
                mainViewModel.selectDestination("status");
            } else if (itemId == R.id.navigation_calls) {
                mainViewModel.selectDestination("calls");
            } else {
                return false;
            }

            return true;
        });

        mainViewModel.getSelectedDestination().observe(this, this::showDestination);
    }

    private void observeConversations(String filter) {

        if (activeConversationSource != null) {
            activeConversationSource.removeObserver(conversationObserver);
        }

        switch (filter) {
            case "unread":
                activeConversationSource =
                        conversationViewModel.observeUnreadConversations();
                break;

            case "favorites":
                activeConversationSource =
                        conversationViewModel.observeFavoriteConversations();
                break;

            case "groups":
                activeConversationSource =
                        conversationViewModel.observeGroupConversations();
                break;

            case "archived":
                activeConversationSource =
                        conversationViewModel.observeArchivedConversations();
                break;

            case "all":
            default:
                activeConversationSource =
                        conversationViewModel.observeAllConversations();
                break;
        }

        activeConversationSource.observe(this, conversationObserver);
    }

    private void showDestination(String destination) {
        if (destination == null) {
            return;
        }

        switch (destination) {
            case "contacts":
                newChatButton.setVisibility(View.GONE);
                chatFilterGroup.setVisibility(View.GONE);
                conversationRecyclerView.setVisibility(View.GONE);
                emptyChatsView.setVisibility(View.VISIBLE);
                emptyTitle.setText(R.string.contacts_title);
                emptyMessage.setText(R.string.contacts_message);
                break;

            case "status":
                newChatButton.setVisibility(View.GONE);
                chatFilterGroup.setVisibility(View.GONE);
                conversationRecyclerView.setVisibility(View.GONE);
                emptyChatsView.setVisibility(View.VISIBLE);
                emptyTitle.setText(R.string.status_title);
                emptyMessage.setText(R.string.status_message);
                break;

            case "calls":
                newChatButton.setVisibility(View.GONE);
                chatFilterGroup.setVisibility(View.GONE);
                conversationRecyclerView.setVisibility(View.GONE);
                emptyChatsView.setVisibility(View.VISIBLE);
                emptyTitle.setText(R.string.calls_title);
                emptyMessage.setText(R.string.calls_message);
                break;

            case "chats":
            default:
                newChatButton.setVisibility(View.VISIBLE);
                chatFilterGroup.setVisibility(View.VISIBLE);

                String selectedFilter =
                        mainViewModel.getSelectedChatFilter().getValue();

                observeConversations(
                        selectedFilter == null ? "all" : selectedFilter
                );
                break;
        }
    }
}
