package com.amizo.glowchat.ui.chats;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.DiffUtil;
import androidx.recyclerview.widget.ListAdapter;
import androidx.recyclerview.widget.RecyclerView;

import com.amizo.glowchat.R;
import com.amizo.glowchat.data.local.entity.ConversationEntity;

import java.text.DateFormat;
import java.util.Date;

public class ConversationAdapter
        extends ListAdapter<ConversationEntity, ConversationAdapter.ConversationViewHolder> {

    private final OnConversationClickListener listener;

    public interface OnConversationClickListener {
        void onConversationClick(ConversationEntity conversation);
    }

    private static final DiffUtil.ItemCallback<ConversationEntity> DIFF_CALLBACK =
            new DiffUtil.ItemCallback<ConversationEntity>() {
                @Override
                public boolean areItemsTheSame(
                        @NonNull ConversationEntity oldItem,
                        @NonNull ConversationEntity newItem
                ) {
                    return oldItem.conversationId.equals(newItem.conversationId);
                }

                @Override
                public boolean areContentsTheSame(
                        @NonNull ConversationEntity oldItem,
                        @NonNull ConversationEntity newItem
                ) {
                    return oldItem.conversationId.equals(newItem.conversationId)
                            && oldItem.type.equals(newItem.type)
                            && equal(oldItem.title, newItem.title)
                            && equal(oldItem.avatarPath, newItem.avatarPath)
                            && oldItem.lastActivityAt == newItem.lastActivityAt
                            && oldItem.unreadCount == newItem.unreadCount
                            && oldItem.isPinned == newItem.isPinned
                            && oldItem.isFavorite == newItem.isFavorite
                            && oldItem.pinnedOrder == newItem.pinnedOrder
                            && oldItem.isArchived == newItem.isArchived;
                }

                private boolean equal(String first, String second) {
                    return first == null ? second == null : first.equals(second);
                }
            };

    public ConversationAdapter(OnConversationClickListener listener) {
        super(DIFF_CALLBACK);
        this.listener = listener;
    }

    @NonNull
    @Override
    public ConversationViewHolder onCreateViewHolder(
            @NonNull ViewGroup parent,
            int viewType
    ) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_conversation, parent, false);

        return new ConversationViewHolder(view);
    }

    @Override
    public void onBindViewHolder(
            @NonNull ConversationViewHolder holder,
            int position
    ) {
        ConversationEntity conversation = getItem(position);
        holder.bind(conversation, listener);
    }

    static class ConversationViewHolder extends RecyclerView.ViewHolder {

        private final ImageView avatar;
        private final TextView name;
        private final TextView time;
        private final TextView preview;
        private final TextView indicators;
        private final TextView unreadCount;

        ConversationViewHolder(@NonNull View itemView) {
            super(itemView);

            avatar = itemView.findViewById(R.id.image_conversation_avatar);
            name = itemView.findViewById(R.id.text_conversation_name);
            time = itemView.findViewById(R.id.text_conversation_time);
            preview = itemView.findViewById(R.id.text_conversation_preview);
            indicators = itemView.findViewById(R.id.text_conversation_indicators);
            unreadCount = itemView.findViewById(R.id.text_unread_count);
        }

        void bind(
                ConversationEntity conversation,
                OnConversationClickListener listener
        ) {
            name.setText(
                    conversation.title == null || conversation.title.trim().isEmpty()
                            ? itemView.getContext().getString(R.string.unknown_conversation)
                            : conversation.title
            );

            preview.setText(
                    itemView.getContext().getString(R.string.no_messages_yet)
            );

            if (conversation.lastActivityAt > 0) {
                Date date = new Date(conversation.lastActivityAt);
                time.setText(DateFormat.getTimeInstance(DateFormat.SHORT).format(date));
            } else {
                time.setText("");
            }

            avatar.setImageResource(
                    android.R.drawable.ic_menu_myplaces
            );

            StringBuilder status = new StringBuilder();

            if (conversation.isPinned) {
                status.append("📌");
            }

            if (conversation.isFavorite) {
                if (status.length() > 0) {
                    status.append("  ");
                }
                status.append("★");
            }

            indicators.setText(status.toString());

            if (conversation.unreadCount > 0) {
                unreadCount.setVisibility(View.VISIBLE);
                unreadCount.setText(
                        conversation.unreadCount > 99
                                ? "99+"
                                : String.valueOf(conversation.unreadCount)
                );
            } else {
                unreadCount.setVisibility(View.GONE);
            }

            itemView.setOnClickListener(
                    view -> listener.onConversationClick(conversation)
            );
        }
    }
}
