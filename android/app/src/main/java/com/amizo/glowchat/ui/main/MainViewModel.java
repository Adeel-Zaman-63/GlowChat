package com.amizo.glowchat.ui.main;

import androidx.lifecycle.LiveData;
import androidx.lifecycle.MutableLiveData;
import androidx.lifecycle.ViewModel;

public class MainViewModel extends ViewModel {

    private final MutableLiveData<String> selectedDestination =
            new MutableLiveData<>("chats");

    private final MutableLiveData<String> selectedChatFilter =
            new MutableLiveData<>("all");

    private final MutableLiveData<Boolean> searchVisible =
            new MutableLiveData<>(false);

    private final MutableLiveData<String> searchQuery =
            new MutableLiveData<>("");

    public LiveData<String> getSelectedDestination() {
        return selectedDestination;
    }

    public LiveData<String> getSelectedChatFilter() {
        return selectedChatFilter;
    }

    public LiveData<Boolean> getSearchVisible() {
        return searchVisible;
    }

    public LiveData<String> getSearchQuery() {
        return searchQuery;
    }

    public void selectDestination(String destination) {
        selectedDestination.setValue(destination);
    }

    public void selectChatFilter(String filter) {
        selectedChatFilter.setValue(filter);
    }

    public void setSearchVisible(boolean visible) {
        searchVisible.setValue(visible);
    }

    public void setSearchQuery(String query) {
        searchQuery.setValue(query);
    }
}
